\# Máquina de estados: Tarea



Los estados y las transiciones están declarados en un solo lugar: `src/estados\_tarea.php`.



\## Estados



| Estado | Significado | Terminal |

|---|---|---|

| pendiente | La tarea fue creada y nadie ha empezado | No |

| en\_progreso | Se está trabajando en ella | No |

| en\_revision | El trabajo terminó y espera aprobación | No |

| completada | Aprobada y cerrada | Sí |



\## Transiciones permitidas



| Desde | Hacia | Quién la ejecuta | Condición |

|---|---|---|---|

| pendiente | en\_progreso | Estándar o Administrador | Ninguna |

| en\_progreso | en\_revision | Estándar o Administrador | Ninguna |

| en\_revision | completada | Administrador | La revisión se aprueba |

| en\_revision | en\_progreso | Administrador | La revisión se rechaza |



\## Transiciones prohibidas



| Desde | Hacia | Motivo |

|---|---|---|

| pendiente | completada | No se puede completar sin trabajarla ni revisarla |

| completada | en\_progreso | Una tarea completada no se reabre |



\## Estado terminal



`completada`: de ella no sale ninguna transición.

