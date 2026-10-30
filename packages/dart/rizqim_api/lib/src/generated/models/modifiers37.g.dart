// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers37.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers37 _$Modifiers37FromJson(Map<String, dynamic> json) => Modifiers37(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers37ToJson(Modifiers37 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
