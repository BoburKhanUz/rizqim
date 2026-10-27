// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data81.g.dart';

@JsonSerializable()
class Data81 {
  const Data81({required this.topupUuid, required this.paymentUrl});

  factory Data81.fromJson(Map<String, Object?> json) => _$Data81FromJson(json);

  @JsonKey(name: 'topup_uuid')
  final String topupUuid;
  @JsonKey(name: 'payment_url')
  final String paymentUrl;

  Map<String, Object?> toJson() => _$Data81ToJson(this);
}
