# The Field

## Setting a location

Authors can set a location three ways:

- **Search** — start typing an address or place and pick a suggestion.
- **Click the map** — drops the pin where you click and looks up the address.
- **Drag the pin** — fine-tune the position; the address updates to match.

The address details (full address, street, suburb, postcode, country) and the coordinates fill in automatically and can be edited by hand. **Clear** removes the location.

## Drawing shapes

Use the toolbar on the left of the map to add markers, lines, rectangles and polygons, then edit, move, cut or delete them. Click a shape to open its panel, where you can add properties (text, numbers, true/false, colours or JSON) and copy its GeoJSON.

**Edit GeoJSON** opens the GeoJSON for the whole field beside the map. Changes in the editor update the map and vice versa. **Upload** loads a `.geojson` file and **Download** saves the current shapes.

::: tip
GeoJSON has no circle type, so circle tools aren’t offered — draw a polygon instead.
:::

## Field settings

| Setting | What it does |
|---|---|
| Initial Location | Where the map starts for entries without a location. Click the map or drag the pin. |
| Enable Drawing & GeoJSON | Shows the drawing tools and GeoJSON buttons. Turn off when you only need a single location; existing shapes are kept. |
| Hide Search | Hides the address search. Authors can still click the map or type coordinates. |
| Hide Address | Hides the address and coordinate inputs. Values are still saved from search and the map. |
| Hide Map | Shows only search and the address inputs. Also hides drawing. |
| Map Height | Height of the map in the control panel, in pixels. |
| Min Zoom / Max Zoom | How far authors can zoom out and in. |
| Preferred Country | Limits address search to one country. |

## Validation

Geo Maps checks that latitude and longitude are numbers in range and are set together, and that GeoJSON is valid (types, coordinates and nesting). Problems are shown on the field when the entry is saved.
