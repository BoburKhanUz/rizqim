// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data35.g.dart';

@JsonSerializable()
class Data35 {
  const Data35({required this.topupUuid, required this.paymentUrl});

  factory Data35.fromJson(Map<String, Object?> json) => _$Data35FromJson(json);

  @JsonKey(name: 'topup_uuid')
  final String topupUuid;
  @JsonKey(name: 'payment_url')
  final String paymentUrl;

  Map<String, Object?> toJson() => _$Data35ToJson(this);
}
