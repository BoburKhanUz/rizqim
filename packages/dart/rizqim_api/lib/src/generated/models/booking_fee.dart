// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'booking_fee.g.dart';

@JsonSerializable()
class BookingFee {
  const BookingFee({
    required this.name,
    required this.mxik,
    required this.packageCode,
    required this.unit,
  });

  factory BookingFee.fromJson(Map<String, Object?> json) =>
      _$BookingFeeFromJson(json);

  final String name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String unit;

  Map<String, Object?> toJson() => _$BookingFeeToJson(this);
}
