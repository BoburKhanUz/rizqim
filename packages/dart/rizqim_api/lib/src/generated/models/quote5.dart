// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'quote5.g.dart';

@JsonSerializable()
class Quote5 {
  const Quote5({
    required this.currency,
    required this.rate,
    required this.total,
  });

  factory Quote5.fromJson(Map<String, Object?> json) => _$Quote5FromJson(json);

  final String currency;
  final String rate;
  final String total;

  Map<String, Object?> toJson() => _$Quote5ToJson(this);
}
