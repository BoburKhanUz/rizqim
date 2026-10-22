// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers25 _$Modifiers25FromJson(Map<String, dynamic> json) => Modifiers25(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers25ToJson(Modifiers25 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
