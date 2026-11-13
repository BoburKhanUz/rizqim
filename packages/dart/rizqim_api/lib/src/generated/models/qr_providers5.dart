// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'qr_providers5.g.dart';

@JsonSerializable()
class QrProviders5 {
  const QrProviders5({
    required this.provider,
    required this.count,
    required this.amount,
  });

  factory QrProviders5.fromJson(Map<String, Object?> json) =>
      _$QrProviders5FromJson(json);

  final String provider;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$QrProviders5ToJson(this);
}
