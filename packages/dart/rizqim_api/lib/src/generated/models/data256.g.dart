// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data256.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data256 _$Data256FromJson(Map<String, dynamic> json) => Data256(
  bill: Bill4.fromJson(json['bill'] as Map<String, dynamic>),
  order: Order7.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data256ToJson(Data256 instance) => <String, dynamic>{
  'bill': instance.bill,
  'order': instance.order,
};
