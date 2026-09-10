# Task Specification – REST API (Symfony & Docker)

> English translation of the original Polish brief.
> Kept for reference so the requirements stay unambiguous while building the project.

## Mission: Build an API that runs like a Swiss watch! 🕰️

Your task is to design and implement a solid REST API that handles the management of
products and categories without trouble. If you code faster than Mr. Robot and have an
eye for detail, this task is for you!

## Technology stack

- 🚀 **Symfony** – the latest stable version.
- **MySQL** – because the data has to live somewhere.
- 🌐 **API Platform** – welcome, but not required.
- 🐳 **Docker** – for easy setup and startup.

## Requirements

### ✔ Entities

- **Product:** id, name, price, date added, date updated.
- **Category:** id, code (unique, max 10 characters), date added, date updated.
- A product must belong to at least one category.

### ✔ Validation

- The `code` field on a category must be unique and no longer than 10 characters.
- The "date added" and "date updated" fields must be set automatically.

### ✔ API functionality

- Adding, updating, deleting, and retrieving products.
- Associating a product with categories.

### ✔ Notifications

After a product and its associated categories have been saved:

- Save an operation log.
- Send an email (it may be fake, but the structure should be ready).

The project should be ready for easy extension with other notifications, e.g. Slack, SMS.

### ✔ Tests

- **PHPUnit** – write tests for the fragment of code that sends notifications.

### ✔ Documentation

- **README.md** – instructions for installing, configuring, and running the project.

### ✔ Repository

- Put the project in any Git repository and share the link.
