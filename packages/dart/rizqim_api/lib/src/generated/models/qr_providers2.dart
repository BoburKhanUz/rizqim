// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'qr_providers2.g.dart';

@JsonSerializable()
class QrProviders2 {
  const QrProviders2({
    required this.provider,
    required this.count,
    required this.amount,
  });

  factory QrProviders2.fromJson(Map<String, Object?> json) =>
      _$QrProviders2FromJson(json);

  final String provider;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$QrProviders2ToJson(this);
}
