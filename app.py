import os
from flask import Flask, render_template, request, flash, redirect, url_for
from flask_mail import Mail, Message
from dotenv import load_dotenv

load_dotenv()  # lee el archivo .env

app = Flask(__name__)
app.config["SECRET_KEY"] = os.getenv("SECRET_KEY", "dev-key-cambiame")

app.config["MAIL_SERVER"] = "smtp.gmail.com"
app.config["MAIL_PORT"] = 587
app.config["MAIL_USE_TLS"] = True
app.config["MAIL_USERNAME"] = os.getenv("MAIL_USERNAME")
app.config["MAIL_PASSWORD"] = os.getenv("MAIL_PASSWORD")

mail = Mail(app)


@app.route("/")
def inicio():
    return render_template("index.html")


@app.route("/empresa")
def empresa():
    return render_template("empresa.html")


@app.route("/servicios")
def servicios():
    return render_template("servicios.html")


@app.route("/sectores")
def sectores():
    return render_template("sectores.html")


@app.route("/proyectos")
def proyectos():
    return render_template("proyectos.html")


@app.route("/como-trabajamos")
def como_trabajamos():
    return render_template("como-trabajamos.html")


@app.route("/conocimiento")
def conocimiento():
    return render_template("conocimiento.html")


@app.route("/legal")
def legal():
    return render_template("legal.html")


@app.route("/contacto", methods=["GET", "POST"])
def contacto():
    if request.method == "POST":
        nombre = request.form.get("nombre", "").strip()
        email = request.form.get("email", "").strip()
        mensaje = request.form.get("mensaje", "").strip()

        if not nombre or not email or not mensaje:
            flash("Por favor, rellena todos los campos.", "danger")
            return redirect(url_for("contacto"))

        destino = os.getenv("MAIL_DESTINO")
        if not app.config["MAIL_USERNAME"] or not app.config["MAIL_PASSWORD"] or not destino:
            flash(
                "El envío de correo todavía no está configurado (falta completar .env).",
                "warning",
            )
            return redirect(url_for("contacto"))

        msg = Message(
            subject=f"Nuevo mensaje de {nombre} desde la web",
            sender=app.config["MAIL_USERNAME"],
            recipients=[destino],
            reply_to=email,
            body=f"De: {nombre} ({email})\n\n{mensaje}",
        )
        mail.send(msg)

        flash("¡Gracias! Tu mensaje se ha enviado correctamente.", "success")
        return redirect(url_for("contacto"))

    return render_template("contacto.html")


if __name__ == "__main__":
    app.run(debug=True)
