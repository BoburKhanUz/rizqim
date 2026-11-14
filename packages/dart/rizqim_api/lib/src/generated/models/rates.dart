// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rates.g.dart';

@JsonSerializable()
class Rates {
  const Rates({
    required this.channel,
    required this.percent,
    required this.note,
  });

  factory Rates.fromJson(Map<String, Object?> json) => _$RatesFromJson(json);

  final String channel;
  final String percent;
  final String? note;

  Map<String, Object?> toJson() => _$RatesToJson(this);
}
