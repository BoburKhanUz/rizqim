// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'takeaway.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Takeaway _$TakeawayFromJson(Map<String, dynamic> json) => Takeaway(
  packaging: (json['packaging'] as List<dynamic>)
      .map((e) => Packaging.fromJson(e as Map<String, dynamic>))
      .toList(),
  pickup: json['pickup'] == null
      ? null
      : Pickup.fromJson(json['pickup'] as Map<String, dynamic>),
  businessDay: BusinessDay2.fromJson(
    json['business_day'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$TakeawayToJson(Takeaway instance) => <String, dynamic>{
  'packaging': instance.packaging,
  'pickup': instance.pickup,
  'business_day': instance.businessDay,
};
