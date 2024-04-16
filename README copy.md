**Implementační dokumentace k 2. úloze do IPP 2022/2023**<br/>**Jméno a příjmení:** Oleksandr Turytsia<br/>**Login:** xturyt00<br/>

## PHP XML interpret [ENG]
This program is an interpreter for files that contain programs written in the IPPcode24 language in XML representation.

### Usage
```
php interpret.php [[--source=[SOURCE_FILE]] [--input=[INPUT_FILE]]] [--help|-h]
```
`--help ` prints the help message.
`--source` specifies the path to the XML file that contains the program to be interpreted.

`--input` specifies the path to a file that contains the input data for the program. 

If source or input were not provided, the interpreter will wait for input from the standard input stream.


### XML Format
The input XML file must conform to the following format:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<program language="IPPcode24">
    <instruction order="[ORDER]" opcode="[OPCODE]">
        <arg1 type="[TYPE]">[VALUE]</arg1>
        <arg2 type="[TYPE]">[VALUE]</arg2>
        <arg3 type="[TYPE]">[VALUE]</arg3>
    </instruction>
    ...
</program>
```

The program element must have a `language` attribute with the value *IPPcode24*.

Each instruction element represents a single instruction in the program, and must have the following attributes:

`order`: an integer representing the order of the instruction in the program
`opcode`: a string representing the opcode of the instruction

Each instruction element may have up to 3 arg elements, each with the following attributes:

`type`: a string representing the type of the argument ("int", "string", "bool", "nil", "var", "type" or "nil")
`value`: the value of the argument, represented as a string


### Implementation

#### Idea
Since the interpret had to be implemented in Python, I have decided to stick with OOP approach as much as it was possible for me.

I divided task into smaller subtasks:
- Handle options, input and basic output
- Parse XML using [ETree](https://docs.python.org/3/library/xml.etree.elementtree.html).
- Implement types using classes. (Var is subset for Symb)
- Implement data stack, frame stack and call stack.
- Create error handling mechanism, that is native to Python.
- Implement static semantic analysis, variable checking. Find all the labels that are being used in the code.
- Implement instructions and operations. Start testing.

For most subtasks was created its class that would
implement all the necessary features in order to work correctly.

In the next sections I will note some of these classes and approaches I followed to ease development.

#### Error handling
Interpret has its own error codes and messages that needs to be shown to a user in some error cases.

I handle it in a HelperFunctions class through the function validateErrorCode and I use the returnCodes from a given ipp\core.


#### ParserXML
For XML parsing interpret is using not only ETree, that I mentioned earlier, but also class `Parser`. It's made to analyze correctness and validity of a language hidden behind XML representation (IFJcode23).

When implementing this class, I have noticed that most of its subclasses were sharing the same idea but with different functionalit, so I decided to created [abstract](https://docs.python.org/3/library/abc.html) class `_GenericParseType`.

#### Types

In interpret `Var` literal is a subset for `Symb`. To implement such behaviour I have used inheritance (see diagram).



