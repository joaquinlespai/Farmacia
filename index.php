<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Personal - CMS Basico</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #24313f;
            background: #f4f7f6;
        }

        header {
            background: #1f6f68;
            color: #ffffff;
            padding: 42px 20px;
            text-align: center;
        }

        header h1 {
            font-size: 2.4rem;
            margin-bottom: 10px;
        }

        header p {
            max-width: 760px;
            margin: 0 auto;
            font-size: 1.05rem;
        }

        main {
            max-width: 980px;
            margin: 32px auto;
            padding: 0 20px;
        }

        section {
            background: #ffffff;
            border: 1px solid #dde7e5;
            border-radius: 8px;
            margin-bottom: 22px;
            padding: 24px;
        }

        h2 {
            color: #1f6f68;
            margin-bottom: 14px;
            font-size: 1.45rem;
        }

        ul {
            padding-left: 22px;
        }

        li {
            margin-bottom: 8px;
        }

        .integrantes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            list-style: none;
            padding-left: 0;
        }

        .integrantes li {
            background: #e8f4f2;
            border-left: 5px solid #1f6f68;
            border-radius: 6px;
            padding: 12px;
            font-weight: bold;
        }

        .crud {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .crud article {
            border: 1px solid #d5e1df;
            border-radius: 8px;
            padding: 16px;
            background: #fbfdfd;
        }

        .crud h3 {
            color: #2a4d69;
            margin-bottom: 8px;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #ffffff;
            background: #24313f;
        }
    </style>
</head>
<body>
    <header>
        <h1>Blog Personal - CMS Basico</h1>
        <p>Aplicacion web dinamica para publicar articulos, gestionar contenido y permitir comentarios de visitantes.</p>
    </header>

    <main>
        <section>
            <h2>Integrantes</h2>
            <ul class="integrantes">
                <li>Tomas Teihuel</li>
                <li>Gerardo Ceron</li>
                <li>Joaquin Lespai</li>
            </ul>
        </section>

        <section>
            <h2>Descripcion de la aplicacion web dinamica</h2>
            <p>
                El Blog Personal es un CMS basico que permite a un administrador crear y administrar
                publicaciones dentro de un sitio web. Los visitantes pueden leer los articulos publicados
                y participar dejando comentarios en cada entrada. Esta aplicacion combina gestion de
                contenido, interaccion con usuarios y administracion de comentarios.
            </p>
        </section>

        <section>
            <h2>Operaciones CRUD</h2>
            <div class="crud">
                <article>
                    <h3>Crear</h3>
                    <p>Publicar un nuevo articulo con titulo, contenido, etiquetas e imagen destacada.</p>
                </article>

                <article>
                    <h3>Leer</h3>
                    <p>Mostrar articulos en la pagina principal y una vista individual con sus comentarios.</p>
                </article>

                <article>
                    <h3>Actualizar</h3>
                    <p>Editar articulos existentes, ademas de moderar o editar comentarios publicados.</p>
                </article>

                <article>
                    <h3>Eliminar</h3>
                    <p>Borrar articulos o eliminar comentarios ofensivos para mantener el sitio ordenado.</p>
                </article>
            </div>
        </section>
    </main>

    <footer>
        <p>Proyecto: Blog Personal CMS Basico</p>
    </footer>
</body>
</html>
