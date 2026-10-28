// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'invoice.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Invoice _$InvoiceFromJson(Map<String, dynamic> json) => Invoice(
  uuid: json['uuid'] as String,
  number: json['number'] as String,
  amount: json['amount'] as String,
  outstanding: json['outstanding'] as String,
  dueDate: json['due_date'] as String,
);

Map<String, dynamic> _$InvoiceToJson(Invoice instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'number': instance.number,
  'amount': instance.amount,
  'outstanding': instance.outstanding,
  'due_date': instance.dueDate,
};
