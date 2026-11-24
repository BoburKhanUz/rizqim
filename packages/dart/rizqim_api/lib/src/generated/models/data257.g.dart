// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data257.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data257 _$Data257FromJson(Map<String, dynamic> json) => Data257(
  bill: Bill5.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data257ToJson(Data257 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
