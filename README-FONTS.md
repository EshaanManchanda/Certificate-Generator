# 🎨 Certificate Generator Font System

## Quick Start

### Automated Setup (Recommended)
```bash
# Make script executable and run
chmod +x setup-fonts.sh
./setup-fonts.sh
```

This single command will:
- Download 20+ popular Google Fonts
- Convert them to FPDF-compatible format
- Add them to your plugin automatically
- Clean up temporary files

### Manual Setup

#### 1. Download Fonts
```bash
chmod +x download-fonts.sh
./download-fonts.sh
```

#### 2. Convert to FPDF Format
```bash
php convert-fonts.php
```

## Available Font Categories

### 📝 Sans-Serif (Clean & Professional)
- **Open Sans** - Clean, professional, highly readable
- **Poppins** - Modern geometric, perfect for headers
- **Montserrat** - Elegant, great for certificates
- **Roboto** - Google's signature font, clean lines
- **Lato** - Rounded feel, friendly appearance
- **Raleway** - Minimal and elegant
- **Noto Sans** - Universal language support

### 📖 Serif (Traditional & Formal)
- **Merriweather** - Classic serif, excellent readability
- **Playfair Display** - Luxury, formal, high-contrast
- **Libre Baskerville** - Traditional serif, book-like
- **Cinzel** - Classical Roman style, decorative
- **Noto Serif** - Universal serif support

### ✍️ Script (Signatures & Decorative)
- **Great Vibes** - Elegant script, perfect for signatures
- **Dancing Script** - Handwritten style, casual elegance
- **Pacifico** - Fun, casual script font
- **Kaushan Script** - Bold handwritten style
- **Satisfy** - Signature-style script

### 🎯 Display (Bold & Attention-Grabbing)
- **Oswald** - Bold, condensed, great for headers
- **Anton** - Heavy display font, very bold

## Font Usage Examples

### Professional Certificates
- **Header**: Montserrat Bold or Cinzel Bold
- **Body Text**: Open Sans or Merriweather
- **Signatures**: Great Vibes or Satisfy

### Modern Certificates
- **Header**: Poppins Bold or Oswald
- **Body Text**: Roboto or Lato
- **Accents**: Raleway

### Traditional/Academic Certificates
- **Header**: Playfair Display or Libre Baskerville Bold
- **Body Text**: Merriweather or Noto Serif
- **Signatures**: Great Vibes

## Technical Details

### Font File Structure
```
includes/fpdf/font/
├── opensans.php          # Open Sans Regular
├── opensans.z            # Compressed font data
├── opensansb.php         # Open Sans Bold
├── poppins.php           # Poppins Regular
├── poppinsb.php          # Poppins Bold
└── ...
```

### How It Works
1. **FontManager Class** automatically scans the font directory
2. **Dynamic Discovery** - any new `.php` font file is automatically detected
3. **Admin Integration** - fonts appear in dropdown automatically
4. **PDF Generation** - fonts are loaded on-demand during certificate creation

### Adding Custom Fonts

#### Method 1: TTF Conversion
1. Place your `.ttf` file in `temp_fonts/` directory
2. Run `php convert-fonts.php`
3. Font will be converted and added automatically

#### Method 2: Manual Addition
1. Convert TTF using FPDF's makefont tool
2. Place resulting `.php` and `.z` files in `includes/fpdf/font/`
3. FontManager will detect them automatically

## Font Licensing

All included fonts are from Google Fonts and are:
- ✅ **Free for commercial use**
- ✅ **Open source licensed**
- ✅ **Safe for distribution**
- ✅ **No attribution required**

## Troubleshooting

### Fonts Not Appearing
1. Check file permissions on `includes/fpdf/font/` directory
2. Ensure `.php` and `.z` files exist for each font
3. Clear WordPress cache if using caching plugins
4. Check error logs for PHP warnings

### Conversion Errors
1. Ensure PHP CLI is installed
2. Check that TTF files are valid
3. Verify makefont.php exists in `includes/fpdf/makefont/`
4. Check write permissions on font directory

### Font Display Issues
1. Verify font encoding (should be cp1252)
2. Check for special characters in text
3. Use font fallbacks in FontManager
4. Test with simple ASCII text first

## Advanced Usage

### Custom Font Categories
Edit `class-font-manager.php` to add custom font groupings:

```php
private function get_font_categories() {
    return [
        'professional' => ['opensans', 'roboto', 'lato'],
        'elegant' => ['playfair', 'montserrat', 'cinzel'],
        'creative' => ['greatvibes', 'dancingscript', 'pacifico']
    ];
}
```

### Font Previews
Add font preview functionality in admin:

```php
// Add to certificate-post-type.php
echo '<span style="font-family: ' . $font_name . ';">Sample Text</span>';
```

## Support

For font-related issues:
1. Check this README first
2. Verify all files are in place
3. Test with a simple font like Open Sans
4. Check WordPress debug logs
5. Ensure proper file permissions

## Font Alternatives to Canva Exclusives

| Canva Font | Free Alternative | Style Match |
|------------|------------------|-------------|
| Tan Meringue | Playfair Display | Elegant serif display |
| Rumble Brave | Great Vibes | Ornamental script |
| Etna Sans | Oswald | Bold display sans |
| Agrandir | Montserrat | Geometric sans |
| Canva Sans | Open Sans | Clean, rounded sans |

---

🎨 **Happy font styling!** Your certificates will look amazing with this professional font collection.