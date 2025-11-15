# Contributing to BitBlog

Thank you for considering contributing to BitBlog! 🎉

## Development Standards

Please read [DEVELOPER_STANDARDS.md](DEVELOPER_STANDARDS.md) for our code quality philosophy and standards.

## How to Contribute

### Reporting Bugs
- Check existing issues first
- Provide clear reproduction steps
- Include PHP version and environment details

### Suggesting Features
- Open an issue with the `enhancement` label
- Describe the use case and expected behavior
- Consider backward compatibility

### Pull Requests
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes following our standards:
   - No dead code or unused functions
   - All UI text must use `Language::getText()`
   - Add translations for both `de` and `en`
   - Follow PSR-12 coding standards
4. Test your changes thoroughly
5. Commit with clear messages
6. Push to your branch
7. Open a Pull Request

## Code Quality Checklist
- [ ] No unused functions or variables
- [ ] All user-facing text is internationalized
- [ ] Code follows existing patterns
- [ ] No commented-out code
- [ ] Proper error handling
- [ ] Security considerations addressed

## Questions?
Open an issue for discussion!

---
*"Evolution ohne Breaking Changes! 🎉"*
