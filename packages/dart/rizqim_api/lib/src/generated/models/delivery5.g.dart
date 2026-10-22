// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Delivery5 _$Delivery5FromJson(Map<String, dynamic> json) => Delivery5(
  address: json['address'] as String?,
  latitude: json['latitude'] as num?,
  longitude: json['longitude'] as num?,
  zoneName: json['zone_name'] as String?,
);

Map<String, dynamic> _$Delivery5ToJson(Delivery5 instance) => <String, dynamic>{
  'address': instance.address,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'zone_name': instance.zoneName,
};
