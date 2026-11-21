// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';

part 'data266.g.dart';

@JsonSerializable()
class Data266 {
  const Data266({required this.channel, required this.queued});

  factory Data266.fromJson(Map<String, Object?> json) =>
      _$Data266FromJson(json);

  final Channel4 channel;
  final bool queued;

  Map<String, Object?> toJson() => _$Data266ToJson(this);
}
