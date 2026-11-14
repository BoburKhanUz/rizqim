// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'quote3.g.dart';

@JsonSerializable()
class Quote3 {
  const Quote3({
    required this.currency,
    required this.rate,
    required this.total,
  });

  factory Quote3.fromJson(Map<String, Object?> json) => _$Quote3FromJson(json);

  final String currency;
  final String rate;
  final String total;

  Map<String, Object?> toJson() => _$Quote3ToJson(this);
}
