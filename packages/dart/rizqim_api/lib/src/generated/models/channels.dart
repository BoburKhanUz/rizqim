// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'channels.g.dart';

@JsonSerializable()
class Channels {
  const Channels({
    required this.channel,
    required this.label,
    required this.enabled,
    required this.available,
  });

  factory Channels.fromJson(Map<String, Object?> json) =>
      _$ChannelsFromJson(json);

  final String channel;
  final String label;
  final bool enabled;
  final bool available;

  Map<String, Object?> toJson() => _$ChannelsToJson(this);
}
