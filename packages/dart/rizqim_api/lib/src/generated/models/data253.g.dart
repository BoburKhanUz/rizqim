// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data253.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data253 _$Data253FromJson(Map<String, dynamic> json) => Data253(
  bill: Bill5.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data253ToJson(Data253 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
