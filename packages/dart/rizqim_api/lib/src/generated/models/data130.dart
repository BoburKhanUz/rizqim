// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';

part 'data130.g.dart';

@JsonSerializable()
class Data130 {
  const Data130({required this.channel, required this.queued});

  factory Data130.fromJson(Map<String, Object?> json) =>
      _$Data130FromJson(json);

  final Channel4 channel;
  final bool queued;

  Map<String, Object?> toJson() => _$Data130ToJson(this);
}
