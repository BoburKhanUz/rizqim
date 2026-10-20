// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'district.dart';
import 'region.dart';
import 'user.dart';

part 'address.g.dart';

@JsonSerializable()
class Address {
  const Address({
    required this.id,
    required this.userId,
    required this.title,
    required this.address,
    required this.latitude,
    required this.longitude,
    required this.apartment,
    required this.entrance,
    required this.floor,
    required this.intercom,
    required this.comment,
    required this.isDefault,
    required this.createdAt,
    required this.updatedAt,
    required this.regionId,
    required this.districtId,
    this.user,
    this.region,
    this.district,
  });

  factory Address.fromJson(Map<String, Object?> json) =>
      _$AddressFromJson(json);

  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  final String title;
  final String address;
  final String? latitude;
  final String? longitude;
  final String? apartment;
  final String? entrance;
  final String? floor;
  final String? intercom;
  final String? comment;
  @JsonKey(name: 'is_default')
  final bool isDefault;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'region_id')
  final int? regionId;
  @JsonKey(name: 'district_id')
  final int? districtId;
  final User? user;
  final Region? region;
  final District? district;

  Map<String, Object?> toJson() => _$AddressToJson(this);
}
