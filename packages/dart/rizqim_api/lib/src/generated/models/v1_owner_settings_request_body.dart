// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'payment_settings.dart';

part 'v1_owner_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerSettingsRequestBody {
  const V1OwnerSettingsRequestBody({
    required this.isOpen,
    required this.paymentSettings,
    this.logo,
    this.banner,
    this.description,
    this.phone,
    this.address,
    this.locationUrl,
    this.minOrderAmount,
  });

  factory V1OwnerSettingsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerSettingsRequestBodyFromJson(json);

  final String? logo;
  final String? banner;
  final String? description;
  final String? phone;
  final String? address;
  @JsonKey(name: 'location_url')
  final String? locationUrl;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  @JsonKey(name: 'min_order_amount')
  final num? minOrderAmount;
  @JsonKey(name: 'payment_settings')
  final List<PaymentSettings> paymentSettings;

  Map<String, Object?> toJson() => _$V1OwnerSettingsRequestBodyToJson(this);
}
