// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data111.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data111 _$Data111FromJson(Map<String, dynamic> json) => Data111(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  expoScope: json['expo_scope'] as String,
  limits: Limits.fromJson(json['limits'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data111ToJson(Data111 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'expo_scope': instance.expoScope,
  'limits': instance.limits,
  'stations': instance.stations,
  'products': instance.products,
};
