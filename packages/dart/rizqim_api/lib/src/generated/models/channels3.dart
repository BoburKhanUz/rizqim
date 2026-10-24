// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'channels3.g.dart';

@JsonSerializable()
class Channels3 {
  const Channels3({required this.telegram, required this.sms});

  factory Channels3.fromJson(Map<String, Object?> json) =>
      _$Channels3FromJson(json);

  final bool telegram;
  final bool sms;

  Map<String, Object?> toJson() => _$Channels3ToJson(this);
}
