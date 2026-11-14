import uuid
from flask import Flask, render_template, request, jsonify, session
from chatbot import chat
from config import SITE_NAME

app = Flask(__name__)
app.secret_key = str(uuid.uuid4())


@app.route("/")
def index():
    session.setdefault("history", [])
    return render_template("index.html", site_name=SITE_NAME)


@app.route("/chat", methods=["POST"])
def chat_endpoint():
    data    = request.get_json()
    message = (data.get("message") or "").strip()

    if not message:
        return jsonify({"error": "Mesaj boş olamaz."}), 400
    if len(message) > 600:
        return jsonify({"error": "Mesaj çok uzun (max 600 karakter)."}), 400

    history = session.get("history", [])
    result  = chat(history, message)

    history.append({"role": "user",      "content": message})
    history.append({"role": "assistant", "content": result["reply"]})
    session["history"] = history[-20:]   # Son 10 tur

    return jsonify(result)


@app.route("/reset", methods=["POST"])
def reset():
    session["history"] = []
    return jsonify({"status": "ok"})


if __name__ == "__main__":
    app.run(debug=False, host="0.0.0.0", port=5000)
