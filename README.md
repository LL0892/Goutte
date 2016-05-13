#Test Goutte Symfony Bundle

Un bundle Symfony2 pour le parsing de données de divers sites de ventes en ligne.

## Configuration

config.yml complet (détails chapitre suivant):
```
// ...
app:
    sites:
        -
            name:               "abc" // nom du site
            searchType:         "formQuery || urlQuery" // type de recherche

            parseUrl:           "www" // lien utilisé par parser les données
            baseUrl:            "www" // lien utilisé pour avoir un lien fonctionnel sur images/urls

            formNode:           "abc" // l'identifiant du formulaire (lorsque 'formQuery')
            inputKey:           "abc" // nom du champ de recherche (input) (lorsque 'formQuery')
            formInputs:               // si des champs caché supplémentaires à rentrer
                    <key>:           "value"
                    <key>:           "value"
                    <key>:           "value"
                    // ...           "..."
            mainNode:           "a > b" // selecteur CSS à la base de l'objet
            titleNode:          "abc" // selecteur CSS du titre de l'objet
            titleNodeParsing:   "innerHTML" // Défini ou aller chercher les données du titre de l'objet
            priceNode:          "a > b" // selecteur CSS du prix de l'objet
            urlNode:
                    value:          "a > b" // selecteur CSS du l'ien de l'objet
                    type:           "relative || absolute" // url relative ou absolue
            imageNode:
                    value:          "a > b" // selecteur CSS de l'image de l'objet
                    type:           "relative || absolute" // url relative ou absolue

        -
            // config du site suivant, même structure que décrite ...
            // ...
            // ...
// ...
```

N'oubliez pas de mettre les paramètres entre double crochet (exception faite des booléans) !


## Détails de la config

### searchType:
```
    searchType:         "formQuery || urlQuery" // type de recherche
```

Deux valeurs acceptées : **formQuery** ou  **urlQuery**.

Dans le cas d'une recherche en utilisant **formQuery**, on fera une première requête sur la page d'accueil du site,
puis via des sélecteur CSS sélectionnerons un formulaire de recherche présent sur cette page d'accueil.
Ensuite nous utiliserons ce formulaire pour injecter la recherche et obtenir les objets à parser sur la page résultante.

Dans le cas d'une recherche en utilisant **urlQuery**, on fera une requête sur un lien dont la variable en paramêtre
aura la valeur de la recherche que nous faisons.

### parseUrl:
```
    parseUrl:           "www" // lien utilisé par parser les données
```

Le lien de base utilisé par le parseur.

Pour **formQuery**, la page d'accueil que l'on veut parser, avec la variable de locale si possible.
Par exemple: `http://www.melectronics.ch/fr/` avec la valeur de la locale en fin de chaine si applicable.

Pour **urlQuery**, l'url sans la valeur du paramètre de recherche (qui sera remplacé par la valeur de votre recherche).
Par exemple: `https://shop.mediamarkt.ch/fr/search/?s=` dont la valeur de la recherche sera en fin de chaine après le égal.

### baseUrl:
```
    baseUrl:            "www" // lien utilisé pour avoir un lien fonctionnel sur images/urls
```

Url propre sans variables de locale, pour parser les images et urls facilement. Par exemple: `http://www.melectronics.ch`

### formNode:
```
    formNode:           "abc" // l'identifiant du formulaire (lorsque 'formQuery')
```

L'identifiant CSS du formulaire (dans la balise \<form\>).
Par exemple: `#quicksearch` ou `#searchbox`

### inputKey:
>inputKey: "abc" // nom du champ de recherche (lorsque 'formQuery')

Le nom du champ de recherche (dans l'attribut "name" de la balise \<input\>).

Lorsque **formQuery**, va utiliser cette valeur. Dans le cas de **urlQuery**, cette valeur sera ignorée.
Il est alors possible de mettre cette valeur à `null`.

### formInputs:
```
    formInputs:              // si des champs caché supplémentaires à rentrer
            <key>:           "value"
            <key>:           "value"
            <key>:           "value"
            // ...
```

Un ensemble de clé et de valeur pour chaque champs à remplir pour la recherche. Il s'agit des champs souvent caché dans le html
donc non visible dans la page, mais pouvant permettre d'ajouter de nouveaux paramètres à la requête.

Il est possible de ne rien mettre si cette attribut ne doit pas être utilisé, ce qui donnera: `formInputs: null`.

### mainNode
```
    mainNode:           "div > div" // selecteur CSS à la base de l'objet
```
Le sélecteur CSS pointant vers la base des articles à parser. Dans une liste d'objet, pointe donc sur l'objet, pas sur la liste.

### titleNode
```
    titleNode:          "abc" // selecteur CSS du titre de l'objet
```

Le sélecteur CSS du titre de l'objet.

### titleStandardNode
```
    titleNodeParsing:   "innerHTML" // Défini ou aller chercher les données du titre de l'objet
```

Valeur par défaut: `innerHTML`.

Permet de définir ou aller chercher les données du titre. **innerHTML** va chercher la donnée à l'intérieur de la balise
ou se trouve le titre. Tout autre valeur sera le nom de l'attribut ou se trouve le titre de l'objet.
Par exemple: `title` ira chercher le contenu d'une balise comme suit
> \<span title="LE TITRE DE L'OBJET"\>\</span\>

### priceNode
```
    priceNode:          "a > b" // selecteur CSS du prix de l'objet
```

Le sélecteur CSS du prix de l'objet.

### urlNode et imageNode
```
    urlNode:
            value:          "div > div" // selecteur CSS du l'ien de l'objet
            type:           "relative || absolute" // url relative ou absolue
    imageNode:
            value:          "div > div" // selecteur CSS de l'image de l'objet
            type:           "relative || absolute" // url relative ou absolue
```

Pour le champ type, deux valeurs acceptées : **relative** ou  **absolute**.

Les valeurs absolues et relatives des liens permettent de définir comment nous générons le lien des images et des urls
selon comment le site-cible affiche les données à parser dans son code source.