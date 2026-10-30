// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data185.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data185 _$Data185FromJson(Map<String, dynamic> json) => Data185(
  bill: Bill3.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order6.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data185ToJson(Data185 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
