// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'click.g.dart';

@JsonSerializable()
class Click {
  const Click({this.enabled, this.merchantId, this.serviceId, this.secretKey});

  factory Click.fromJson(Map<String, Object?> json) => _$ClickFromJson(json);

  final bool? enabled;
  @JsonKey(name: 'merchant_id')
  final String? merchantId;
  @JsonKey(name: 'service_id')
  final String? serviceId;
  @JsonKey(name: 'secret_key')
  final String? secretKey;

  Map<String, Object?> toJson() => _$ClickToJson(this);
}
