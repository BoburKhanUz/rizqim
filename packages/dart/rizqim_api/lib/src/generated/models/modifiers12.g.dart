// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers12 _$Modifiers12FromJson(Map<String, dynamic> json) => Modifiers12(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers12ToJson(Modifiers12 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
