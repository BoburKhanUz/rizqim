// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'offline.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Offline _$OfflineFromJson(Map<String, dynamic> json) => Offline(
  devicePrefix: json['device_prefix'] as String?,
  nextLocalNumber: (json['next_local_number'] as num).toInt(),
  businessDate: json['business_date'] as String,
  dayType: json['day_type'] as String,
  serviceChargeLocked: json['service_charge_locked'] as bool,
  fees: (json['fees'] as List<dynamic>)
      .map((e) => Fees.fromJson(e as Map<String, dynamic>))
      .toList(),
  places: (json['places'] as List<dynamic>)
      .map((e) => Places.fromJson(e as Map<String, dynamic>))
      .toList(),
  guestItems: (json['guest_items'] as List<dynamic>)
      .map((e) => GuestItems.fromJson(e as Map<String, dynamic>))
      .toList(),
  limits: Limits3.fromJson(json['limits'] as Map<String, dynamic>),
);

Map<String, dynamic> _$OfflineToJson(Offline instance) => <String, dynamic>{
  'device_prefix': instance.devicePrefix,
  'next_local_number': instance.nextLocalNumber,
  'business_date': instance.businessDate,
  'day_type': instance.dayType,
  'service_charge_locked': instance.serviceChargeLocked,
  'fees': instance.fees,
  'places': instance.places,
  'guest_items': instance.guestItems,
  'limits': instance.limits,
};
