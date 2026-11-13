// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data198.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data198 _$Data198FromJson(Map<String, dynamic> json) => Data198(
  bill: Bill4.fromJson(json['bill'] as Map<String, dynamic>),
  copy: json['copy'] as bool,
  order: Order8.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data198ToJson(Data198 instance) => <String, dynamic>{
  'bill': instance.bill,
  'copy': instance.copy,
  'order': instance.order,
};
