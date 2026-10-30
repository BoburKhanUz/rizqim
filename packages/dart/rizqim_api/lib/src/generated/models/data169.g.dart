// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data169.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data169 _$Data169FromJson(Map<String, dynamic> json) => Data169(
  bill: Bill3.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order6.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data169ToJson(Data169 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
