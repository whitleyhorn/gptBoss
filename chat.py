import sys
from flask import Flask, request, jsonify, render_template
from langchain import OpenAI, LLMChain, PromptTemplate
from langchain.chains import ConversationChain
from langchain.chat_models import ChatOpenAI
from langchain.memory import ConversationBufferMemory, ConversationSummaryMemory, ChatMessageHistory
from langchain.schema import (
    AIMessage,
    HumanMessage,
    SystemMessage,
    messages_from_dict,
    messages_to_dict
)
from dotenv import load_dotenv
from mysql.connector import connect, Error
import os
import json
from json import JSONEncoder

system_message = "you are a helpful assistant."
k = 2
human_message_key = "human"

load_dotenv()

def get_api_key():
    return os.getenv("OPENAI_API_KEY")

def get_db_connection():
    return connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        database=os.getenv("DB_NAME"),
    )

def update_conversation(messages, conversation_id):
    dicts = messages_to_dict(messages)
    connection = get_db_connection()
    cursor = connection.cursor()
    cursor.execute(
        "UPDATE conversations SET history = %s WHERE id = %s",
        (json.dumps(dicts), conversation_id)
    )
    connection.commit()


def store_conversation(messages, user_id, app_type):
    dicts = messages_to_dict(messages)
    # Now store dicts instead of the messages directly
    try:
        connection = get_db_connection()
        cursor = connection.cursor()
        cursor.execute(
            "INSERT INTO conversations (history, user_id, app_type) VALUES (%s, %s, %s)",
            (json.dumps(dicts), user_id, app_type)
        )
        connection.commit()
        return cursor.lastrowid  # return conversation_id
    except Error as e:
        print("Error while updating MySQL:", e)


def load_conversation(user_id, app_type):
    connection = get_db_connection()
    cursor = connection.cursor()
    cursor.execute(
        "SELECT history FROM conversations WHERE user_id = %s AND app_type = %s ORDER BY id DESC LIMIT 1",
        (user_id, app_type)
    )
    result = cursor.fetchone()
    if result is not None:
        raw_dicts = json.loads(result[0])
        messages = messages_from_dict(raw_dicts)
        return messages
    else:
        return []

app = Flask(__name__)

@app.route('/chat', methods=['POST'])
def chat():
    data = request.get_json()
    if 'message' not in data or len(data['message']) < 1:
        return jsonify({"error": "Message is required"}), 400
    if 'user_id' not in data or 'app_type' not in data:
        return jsonify({"error": "Both user_id and app_type are required"}), 400

    user_input = data['message']
    user_id = data['user_id']
    app_type = data['app_type']
    conversation_id = data.get('conversation_id')  # conversation_id is optional

    if get_api_key() is None or get_api_key() == "":
        return jsonify({"error": "OpenAI API key is not set"}), 500

    llm = OpenAI(temperature=0)
    history = ChatMessageHistory()
    loaded_messages = load_conversation(user_id, app_type)

    memory = ConversationBufferMemory(memory_key="chat_history")
    for msg in loaded_messages:
        if msg.type == 'human':
            memory.chat_memory.add_user_message(msg.content)
        elif msg.type == 'ai':
            memory.chat_memory.add_ai_message(msg.content)
        elif msg.type == 'system':
            memory.chat_memory.add_system_message(msg.content)

    template = """You are an AI chatbot helping a human to find a lawyer. Your job is to collect specific information sequentially. Begin by asking for the person's first name, followed by the last name, address, phone number, case practice area, and finally a brief description of their case. It's essential that each piece of information is fully obtained before proceeding to the next. Carefully review the chat history to ensure you have collected each piece of information completely. If there's any missing or incomplete data, make sure to ask for it. For instance, if the person has provided their first name but not their last name, your next question should request the last name.
    {chat_history}
    Human: {human_input}
    Chatbot:"""

    prompt = PromptTemplate(
        input_variables=["chat_history", "human_input"],
        template=template
    )

    llm_chain = LLMChain(
        llm=OpenAI(),
        prompt=prompt,
        verbose=True,
        memory=memory,
    )
    output = llm_chain.predict(human_input=user_input)

    messages = llm_chain.memory.chat_memory.messages

    print('messages')
    print(messages)

    if conversation_id:
        # Note: In production, do not allow the user to modify a conversation without ensuring they have permission to do so!
        update_conversation(messages, conversation_id)
    else:
        conversation_id = store_conversation(messages, user_id, app_type)

    return jsonify({"response": output, "conversation_id": conversation_id})

@app.route('/')
def index():
    return render_template('index.html')

if __name__ == '__main__':
    app.run()

