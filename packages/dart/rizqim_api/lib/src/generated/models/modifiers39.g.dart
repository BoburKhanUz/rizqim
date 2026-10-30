// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers39.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers39 _$Modifiers39FromJson(Map<String, dynamic> json) => Modifiers39(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers39ToJson(Modifiers39 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
