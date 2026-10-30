// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers30.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers30 _$Modifiers30FromJson(Map<String, dynamic> json) => Modifiers30(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers30ToJson(Modifiers30 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
