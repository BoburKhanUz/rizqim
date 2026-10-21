// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data60.g.dart';

@JsonSerializable()
class Data60 {
  const Data60({required this.topupUuid, required this.paymentUrl});

  factory Data60.fromJson(Map<String, Object?> json) => _$Data60FromJson(json);

  @JsonKey(name: 'topup_uuid')
  final String topupUuid;
  @JsonKey(name: 'payment_url')
  final String paymentUrl;

  Map<String, Object?> toJson() => _$Data60ToJson(this);
}
