# REST API Plugin for os-Ticket: Final College Project  

**Developed in collaboration with ASNR (Autoridade Nacional de Segurança Rodoviária)**  

## Overview  
As part of my college programming course’s final project, my team and I designed, implemented, and documented a **custom plugin** for the os-Ticket helpdesk system. This plugin introduces a **REST API** that extends os-Ticket’s functionality, enabling programmatic management of support tickets. The solution was developed in partnership with ASNR to address their need for secure, automated ticket management within their existing workflow.  

## Key Features  
- **Ticket Management**: Create, close, edit, and suspend tickets via RESTful endpoints.  
- **Authentication Mechanism**: Secure access control using API keys, ensuring only authorized users with active keys can interact with the API.  
- **Collaboration with ASNR**: Tailored to meet the real-world requirements of a national road safety authority, emphasizing security and reliability.  
- **Comprehensive Documentation**: Detailed technical specifications, usage examples, and integration guidelines.  

## Implementation Details  
- Built as a modular plugin compatible with os-Ticket’s architecture (PHP-based).  
- Authentication system validates API keys against os-Ticket’s user database, enforcing role-based permissions.  
- Endpoints designed for simplicity and scalability, with JSON payloads for seamless integration.  
- Includes error handling, rate limiting, and logging for robust operation.  

## Documentation  
For full technical details, API reference, and setup instructions, visit the project’s GitBook:  
🔗 [**GitBook Documentation**](https://tomasgomesisel.gitbook.io/projeto-ansr-isel)  

---

*Developed as part of a college course requirement, in collaboration with ASNR.*  
