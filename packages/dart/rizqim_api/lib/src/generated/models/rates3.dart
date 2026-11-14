// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel3.dart';

part 'rates3.g.dart';

@JsonSerializable()
class Rates3 {
  const Rates3({required this.channel, required this.percent, this.note});

  factory Rates3.fromJson(Map<String, Object?> json) => _$Rates3FromJson(json);

  final Channel3 channel;
  final num percent;
  final String? note;

  Map<String, Object?> toJson() => _$Rates3ToJson(this);
}
