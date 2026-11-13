// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';

part 'data202.g.dart';

@JsonSerializable()
class Data202 {
  const Data202({required this.channel, required this.queued});

  factory Data202.fromJson(Map<String, Object?> json) =>
      _$Data202FromJson(json);

  final Channel4 channel;
  final bool queued;

  Map<String, Object?> toJson() => _$Data202ToJson(this);
}
