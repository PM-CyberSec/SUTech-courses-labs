// document.writeln("External JavaScript File Loaded ");

// let temp=30;
// let s;

// if(temp>=100){
//     s="boiling"
// }
// else if(temp<100 && temp>=60){
//     s="warm"
// }
// else{
//     s="cold"
// }
// document.writeln(s);

// if(temp==100){
//     s="boiling"
// }else{
//     s="not boiling"
// }

// s=(temp==100)?"boiling":"not boiling";
// document.writeln(s);

// let day=2;
// switch(day){
//     case 1:
//         document.writeln("sunday");
//         break;

//     case 2:
//         document.writeln("monday");
//         break;
//     default:
//         document.writeln("NOT A DAY");
//         break;
// }

// let jobs="doctor";
// switch(jobs){
//     case "designer":
//         document.writeln("designs");
//         break;

//     case "doctor":
//         document.writeln("treats people");
//         break;
//     default:
//         document.writeln("3awatly");
//         break;
// }

// for(var i=0;i<5;i++){
//     // var k=1;
//     document.writeln(i)
// }
// var c=9;
// while(c<7){
//     document.writeln(c);
//     c++;
// }

// do{
//     document.writeln(c);
//     c++;
// }while(c<7)

// function changeText(txt){
//     txt="Text Changed";
//     console.log("Inside function: txt =", txt);
// }

// let myText="Original Text";
// myText=changeText(myText);
// console.log("Outside function: myText =", myText);

// function changeValue(x) {
//     x = 100;   
//     console.log("Inside function: x =", x);
// }

// let num = 50;

// changeValue(num);

// console.log("Outside function: num =", num);

// const myfunction=function(a,b){
//     return a+b;
// }

// console.log("External JavaScript File Loaded ");
// const myfunction=(a,b)=>{return a+b};

// let myArray = [10, 20, 30,40,50,60];
// // myArray[myArray.length] = 40;
// // console.log(myArray); // Output: [10, 20, 30, 40]
// // myArray.push(50);
// // myArray.pop();
// // myArray.shift();
// // myArray.unshift(5);
// let newArr = myArray.slice(1,3);
// newArr[0]=100;
// console.log(myArray);
// // myArray.splice(0,2,15,"nas","new");
// // myArray.join("-");
// let students = [
//     { name: "Omar" },
//     { name: "Ali" },
//     { name: "Sara" }
// ];

// let copy = students.slice(0, 2); 
// copy[0].name = "Ahmed";

// console.log(students); 
// console.log(copy);
// let myArray = [100, 250, 350, 410, 50, 60];
// let str=myArray.join("-");
// console.log(str);
// let revarr=myArray.reverse();
// console.log(revarr);
// let array2=[70,80,90];
// let combined=myArray.concat(array2);
// console.log(combined);
// let sorted=myArray.sort((a,b)=>a-b);
// console.log(sorted);
// let desc=myArray.sort((a,b)=>b-a);
// console.log(desc);
// for(let i=0;i<myArray.length;i++){
//     console.log(myArray[i]);
// }

// for(let element of myArray){
//     console.log(element);
// }

// myArray.forEach((element)=>{
//     console.log(element);
// });

// let myArray = [10, 21, 35, 40, 56, 69, 77, 80, 90, 101];
// let newarr = myArray.map((element) => {
//     return element * 2;
// })
// console.log(newarr);
// console.log(myArray);
// let filtered = myArray.filter((element) => {
//     return element % 2 === 0;
// });

// let product=myArray.reduce((accumulator,element)=>{
//     return accumulator*element;
// },1);
// console.log(filtered);
// console.log(product);

// DOM Methods
// 1-Accessing Elements

// getElementBy(Id,ClassName,TagName)
// element.querySelector(first match css selector)
// element.querySelectorAll(css selector) but returns all matches

// 2-Change Element Content
// element.(innerHTML:change it with a HTML content,textContent:change it with plain text)

// 3-Change Element Attributes
// element.getAttribute(attrName)
// element.setAttribute(attrName,attrValue)

// 4-Change Element Styles
// element.style.property= value
// element.classList.add/remove/toggle:add if not exsist remove if exists(className)

// 5-Create/Remove Elements

// not added to the document yet
// to add it create it and append it to a parent element
// document.createElement(tagName)
// document.createTextNode(textContent):plain text

// added to the document
// get element by id then append or remove child

// parentElement.appendChild(childElement)
// parentElement.removeChild(childElement)

//6- Events
//click , hover, type, load (page loads),submit(form) ,change(value of input)
//element.addEventListener(event, function) :

//lab 25/11/2025 
//functions and arrays

// function hello(){
//     var u=1;
//     console.log("hello")
// }
// // console.log(u); //gives error
// hello();
// console.log(hello());

// function subtract(a=7,b=3){
//     var result= a-b;
//     // console.log(result);
//     return result;
// }
// // subtract();
// // subtract(8,6);
// // subtract()
// subtract(5,2);
// console.log(subtract(9,4));

// var y=function(){
//     console.log("hello this is y");
// }
// y();

// function first(fn){
//     console.log("this is the first function")
//     fn();
// }

// first(y);

// first(function(n1=4,n2=18){
//     var sum=n1+n2;
//     console.log(sum);
// });

// first(function(n1=15,n2=11){
//     var difference=n1-n2;
//     console.log(difference);
// });

// for(var i=0;i<5;i++){
//     var k=1;//function scope or global
//     let j=1;//block  or function scope
//     document.writeln(i)
// }
// console.log(k);
// // console.log(j);

// var myArray=[1,2,3,"Omar","Data Science",1.5,900,"AI",800];

// console.log(myArray[5]);
// myArray[3]="Omar Nasr"
// console.log(myArray);
// console.log(myArray.length);
// myArray.pop();
// myArray.push(45,"Engineer");
// console.log(myArray);
// myArray.splice(3);//delete from index 3 to the end (shallow copy)
// console.log(myArray);
// myArray.splice(1,1);//go to index 1 then delete one element
// console.log(myArray);
// myArray.splice(1,1,"Omar")
// console.log(myArray);//go to index 1 then delete one element and replace it with"omar"
// myArray.push(45,"Engineer","Data Science",1.5,900,"AI",800);
// console.log(myArray);
// myArray.shift();//remove first element
// console.log(myArray);
// myArray.unshift(100,200,300);//add elements to the beginning of the array
// console.log(myArray);

// var arr=[12,45,66,45,77,80,45]
// console.log(arr.indexOf(45));
// console.log(arr.lastIndexOf(45));

// var newarr=arr.slice(2,6)//take from 2:6 excluding 6 and put them in an array
// console.log(newarr);

//lab 12/2/2025

//continue arrays 
// var arr=[1,3,4]
// var arr2=[1,5,7]
// var newArr=arr.concat(arr2);
// console.log(newArr);

// var str=["Welcome","Omar","Nasr","Hassan"]
// var joined=str.join("-")
// console.log(joined);

// var arr2=["Banana","Apple","Mango","Orange"]
// arr2.sort()
// console.log(arr2);

// var num=[10,9,5,4,20,15]
// num.sort()
// console.log(num);
// //wrong sorting because it sees here only the first digit
// //so 3>100000000

// //to fix it we use a compare function
// var asc=num.sort((a,b)=>a-b)//ascendings
// console.log(asc);
// var desc=num.sort((a,b)=>b-a)//descending
// console.log(desc);

// //iterating on arrays
// console.log("normal for loop:");
// for (var i = 0; i < num.length; i++) {
//     console.log(`num: ${num[i]}`);
// }

// console.log("for of loop:");
// for(var element of num){
//     console.log(`element: ${element}`)
// }

// console.log("for each loop:");
// console.log("adding 1 to each element:");

// num.forEach(function(n){
//     console.log(n + 1);
// });

// //DOM
// //1-accessing elements
// //by id
// var obj1=document.getElementById("Access");
// console.log(obj1);
// //by class name
// var obj2=document.getElementsByClassName("container")[0];
// console.log(obj2);
// //by tag name
// var obj3=document.getElementsByTagName("p")[3];
// console.log(obj3);
// //by query selector (first match)
// var obj4=document.querySelector(".child2");
// console.log(obj4);

// //by query selector all (all matches)
// var obj5=document.querySelectorAll(".child2")[0];
// console.log(obj5);

// //2-change element content
// obj1.innerHTML="First header ,<a href='#'>Changed</a>";

// //3-change element attributes
// var obj5=document.getElementsByTagName("input")[0];
// obj5.setAttribute("value","Nasr Hassan" )

// //4-change element styles
// obj1.style.color="red";

// //5-create/remove elements
// //create element
// var newElement=document.createElement("h1");
// //create text node
// var text=document.createTextNode("This is a new paragraph added by JS");
// //add text to element
// newElement.appendChild(text);
// //add to document
// document.body.appendChild(newElement);

//remove from document
// document.body.removeChild(newElement);  

// //lab 9/12/2025
// //objects , classes , json file

// //objects
// let person={
//     name:"Omar",
//     major : "Data Science and AI",
//     greet:function(){
//         console.log("Hello "+this.name)
//     }
// }
// console.log(person.__proto__);
// person.greet();
// console.log("Major: " + person.major)

// person.name="Omar Nasr"
// console.log("Full Name :"+ person.name)

// person.age=20
// console.log(person)

// delete(person.age)
// console.log(person)

// console.log("keys: "+ Object.keys(person))
// console.log("values: "+ Object.values(person))
// console.log( Object.entries(person))

// let person2={}

// Object.assign(person2,person,{age:20}) 
// console.log(person2)

// //for in (for objects only)
// for(var key in person){
//     console.log(key+" : "+person[key])
// }

// //constructors

// function Person(name,major){
// //assigning parameter to object property
// //this refers to the current object
// //so this.name is the name property of the object
// //we store the value of the parameter name to the property name
//     this.name=name;
//     this.major=major;//same for major
// }
// let p1=new Person("Nasr","Pharmacy");
// let p2=new Person("Hassan","Agricultural Engineering");
// console.log(p1);
// console.log(p2);

// //prototypes
// //old way

// let a=new Array(1,2,3);
// console.log(Array.prototype); 

//constructor function
function Animal(name){
    this.name=name;
}

// adding method to prototype
Animal.prototype.speak=function(){
    console.log(this.name+" makes a sound.");
}

console.log(Animal.prototype);

//inheritance
//dog inherits from animal
function Dog(name){
    Animal.call(this,name);
}

let an= new Animal("Lion");
an.speak();

let d=new Dog("Buddy");
//prototype of dogs that carries all methods 
// of animal which is the parent class of dog
Dog.prototype=Object.create(Animal.prototype);
Dog.prototype.constructor=Dog;

//classes
class Animal2{
    //constructor 
    constructor(name){
        this.name=name;
    }

    //methods
    sound(){
        console.log(this.name+" makes a sound.");
    }

}

class Cat extends Animal2{
    //constructor of the child class
    constructor(name , color){
        //call the constructor of the parent class
        super(name)
        this.color=color
    }

    sound(){
        console.log(this.name+" makes a sound ");
    }
}

//create object of dog2
let d2=new Animal2("memo") 
let c2=new Cat("kitty","White")
d2.sound()
c2.sound()

//JSON
//convert object to JSON string
let str =JSON.stringify(person)
console.log(str);

//convert JSON string to object
let obj2=JSON.parse(str)
console.log(obj2);

//self study 14/12/2025
//Synchronous-Asynchronous JavaScript
// //Synchronous Example
// console.log("Start");

// alert("This is synchronous");

// console.log("End");

// console.log("Start");

//Asynchronous Example
// console.log("Start");

// setTimeout(() => {
//     console.log("Hello after 2 seconds");
// },2000);

// console.log("End");

//JSON

// const jsonString = '{"name":"Omar","age":20}';
// const obj = { name: "Omar", age: 20 };
// let tojson=JSON.stringify(obj);
// let toobj=JSON.parse(jsonString);
// console.log(tojson);
// console.log(toobj);

// let promise = new Promise(function(resolve, reject) {
//     setTimeout(() => {
//         let success = true;
//         if (success) {
//             resolve("Task completed");   // fulfilled
//         } else {
//             reject("Task failed");      // rejected
//         }
//     }, 2000);
// });

// // Attaching handlers
// promise
//     .then(result => console.log(result))   // runs if resolved
//     .catch(error => console.log(error));   // runs if rejected

// console.log("After promise");
