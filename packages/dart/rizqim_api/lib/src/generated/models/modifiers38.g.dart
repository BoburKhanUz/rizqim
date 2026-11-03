// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers38.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers38 _$Modifiers38FromJson(Map<String, dynamic> json) => Modifiers38(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers38ToJson(Modifiers38 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
