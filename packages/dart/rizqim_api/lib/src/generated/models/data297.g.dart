// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data297.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data297 _$Data297FromJson(Map<String, dynamic> json) => Data297(
  branch: Branch19.fromJson(json['branch'] as Map<String, dynamic>),
  store: json['store'] == null
      ? null
      : Store3.fromJson(json['store'] as Map<String, dynamic>),
  device: Device12.fromJson(json['device'] as Map<String, dynamic>),
  tillUuid: json['till_uuid'] as String?,
  modules: Modules2.fromJson(json['modules'] as Map<String, dynamic>),
  serverTime: json['server_time'] as String,
  cart: json['cart'] == null
      ? null
      : Cart.fromJson(json['cart'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data297ToJson(Data297 instance) => <String, dynamic>{
  'branch': instance.branch,
  'store': instance.store,
  'device': instance.device,
  'till_uuid': instance.tillUuid,
  'modules': instance.modules,
  'server_time': instance.serverTime,
  'cart': instance.cart,
};
