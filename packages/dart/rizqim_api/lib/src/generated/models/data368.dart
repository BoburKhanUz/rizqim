// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'poll_seconds.dart';
import 'reverb.dart';

part 'data368.g.dart';

@JsonSerializable()
class Data368 {
  const Data368({
    required this.status,
    required this.live,
    required this.heartbeatSeconds,
    required this.silenceSeconds,
    required this.pollSeconds,
    required this.reverb,
  });

  factory Data368.fromJson(Map<String, Object?> json) =>
      _$Data368FromJson(json);

  final String status;
  final bool live;
  @JsonKey(name: 'heartbeat_seconds')
  final int heartbeatSeconds;
  @JsonKey(name: 'silence_seconds')
  final int silenceSeconds;
  @JsonKey(name: 'poll_seconds')
  final PollSeconds pollSeconds;
  final Reverb? reverb;

  Map<String, Object?> toJson() => _$Data368ToJson(this);
}
