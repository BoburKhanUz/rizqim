// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers17.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers17 _$Modifiers17FromJson(Map<String, dynamic> json) => Modifiers17(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers17ToJson(Modifiers17 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
