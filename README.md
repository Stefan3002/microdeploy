# MicroDeploy

**Bridging Content Management and Micro-Frontends: A WordPress-Centric Approach to Modern Web Architecture**  
_Accompanying implementation for the research presented at [AINA 2025, SpringerLink](https://link.springer.com/chapter/10.1007/978-3-031-87766-7_6)_

## Overview

**MicroDeploy** is a prototype system designed to bridge the gap between monolithic CMS platforms (like WordPress) and modern micro-frontend architectures. This project accompanies the research study that proposes a novel approach to integrating modern web development practices with legacy systems, enabling scalable, performant, and modular frontends.

The solution focuses on:
- Decoupling UI concerns from backend CMS logic
- Seamlessly embedding micro-frontends into traditional WordPress workflows
- Achieving measurable performance gains in LCP and DOM construction times

---

## ✨ Features

- **Micro-Frontend Loader**: Dynamically integrates frontend apps (React, Vue, etc.) into WordPress themes/plugins
- **Content-Aware Deployment**: Automates loading logic based on WordPress content type or shortcode usage
- **Performance-Oriented**: Optimized for Largest Contentful Paint (LCP) and DOM rendering efficiency
- **Backward-Compatible**: Works alongside traditional WordPress PHP templates and plugin systems

---

## 🛠 Technologies Used

- WordPress (PHP)

---

## 📖 Setup Instructions

### Requirements

- WordPress installation (local or production)
- PHP 7.4+

### Installation

1. Clone this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/Stefan3002/microdeploy.git
   ```

2. Activate the plugin via the WordPress Admin Panel.

---

## 📊 Performance Highlights

From the study:
- **~50% decrease** in Largest Contentful Paint (LCP)
- **~40% faster** DOM tree construction
- Maintains backward compatibility while enabling progressive enhancement

---

## 📄 Related Publication

> Secrieru, S., Ștefănigă, S.A. (2025). Bridging Content Management and Micro-Frontends: A WordPress-Centric Approach to Modern Web Architecture.  
> In: Barolli, L. (eds) _Advanced Information Networking and Applications_. Lecture Notes on Data Engineering and Communications Technologies, vol 246. Springer, Cham.  
> [DOI: 10.1007/978-3-031-87766-7_6](https://link.springer.com/chapter/10.1007/978-3-031-87766-7_6)

---

## 🧪 Use Cases

- Academic/enterprise sites using WordPress looking to modernize their frontend architecture
- Gradual migration from PHP-based templates to JS-based micro-apps
- Developers looking to integrate React/Vue/Angular with legacy WordPress infrastructure

---

## 📬 Contact

Maintainer: [Ștefan Secrieru](https://stefansecrieru.com)  
GitHub: [@Stefan3002](https://github.com/Stefan3002)

