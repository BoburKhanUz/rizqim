// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data7 _$Data7FromJson(Map<String, dynamic> json) => Data7(
  zone: Zone.fromJson(json['zone'] as Map<String, dynamic>),
  branch: Branch.fromJson(json['branch'] as Map<String, dynamic>),
  deliveryPrice: json['delivery_price'] as String,
  minOrderAmount: json['min_order_amount'] as String,
  freeDeliveryApplies: json['free_delivery_applies'] as bool,
  meetsMinOrder: json['meets_min_order'] as bool?,
  eta: json['eta'] == null
      ? null
      : Eta.fromJson(json['eta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data7ToJson(Data7 instance) => <String, dynamic>{
  'zone': instance.zone,
  'branch': instance.branch,
  'delivery_price': instance.deliveryPrice,
  'min_order_amount': instance.minOrderAmount,
  'free_delivery_applies': instance.freeDeliveryApplies,
  'meets_min_order': instance.meetsMinOrder,
  'eta': instance.eta,
};
