// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data255.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data255 _$Data255FromJson(Map<String, dynamic> json) => Data255(
  bill: Bill5.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data255ToJson(Data255 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
