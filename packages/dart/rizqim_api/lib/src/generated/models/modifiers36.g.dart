// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers36.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers36 _$Modifiers36FromJson(Map<String, dynamic> json) => Modifiers36(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers36ToJson(Modifiers36 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
