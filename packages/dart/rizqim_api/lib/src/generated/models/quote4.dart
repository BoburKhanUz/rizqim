// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'quote4.g.dart';

@JsonSerializable()
class Quote4 {
  const Quote4({
    required this.currency,
    required this.rate,
    required this.total,
  });

  factory Quote4.fromJson(Map<String, Object?> json) => _$Quote4FromJson(json);

  final String currency;
  final String rate;
  final String total;

  Map<String, Object?> toJson() => _$Quote4ToJson(this);
}
