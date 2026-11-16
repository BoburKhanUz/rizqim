// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'poll_seconds.g.dart';

@JsonSerializable()
class PollSeconds {
  const PollSeconds({required this.live, required this.fallback});

  factory PollSeconds.fromJson(Map<String, Object?> json) =>
      _$PollSecondsFromJson(json);

  final int live;
  final int fallback;

  Map<String, Object?> toJson() => _$PollSecondsToJson(this);
}
