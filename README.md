# Pelican Module for FOSSBilling

A free and open-source module that connects **Pelican** with **FOSSBilling**, allowing you to automate game server provisioning, management, and suspension directly from your FOSSBilling panel. This module is designed to streamline hosting operations and provide a seamless experience for both providers and their clients.

> [!CAUTION]
> This module is currently not in a functional state and Pelican itself is still in beta!  
> We **strongly** advise against using (unstable) beta software in production, especially if you have customers.


#Forked from https://github.com/Athenox14/Pterodactyl-Module-FOSSBilling

## ✨ Features

* ✅ **Automatic Server Provisioning** – Deploy servers instantly after payment confirmation.
* ✅ **Suspend / Unsuspend Servers** – Automatic suspension for overdue invoices and instant reactivation on payment.
* ✅ **Multiple Node Support** – Assign products to specific Pelican nodes.
* ✅ **Custom Resource Allocation** – Configure CPU, RAM, disk, and other limits per product plan.
* ✅ **Client Panel Access** – Clients can see their server details directly in FOSSBilling.

## 📦 Requirements

* [FOSSBilling](https://fossbilling.org/) (latest stable version)
* [Pelican](https://pelican.dev/) (latest stable version)
* A working Pelican API key (application API)

## ⚙️ Installation

1. **Download the module** or clone the repository:

   ```bash
   git clone https://github.com/nerdscorp/Pelican-Module-FOSSBilling.git
   ```
2. **Upload the module** to your FOSSBilling `/modules/` directory.
3. **Activate the module** from the FOSSBilling admin panel.
4. **Configure your Pelican credentials** (API URL and keys) in the module settings.
5. **Create products** in FOSSBilling linked to your Pelican servers and plans.

## 🛠 Roadmap

* [ ] - Improve UI/UX for client panel

## 🤝 Contributing

Pull requests and feature suggestions are welcome!

1. Fork the repo
2. Create a feature branch
3. Submit a PR

## 📜 License

This module is licensed under the **MIT License**. See [LICENSE](LICENSE) for details.
