// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'participants.g.dart';

@JsonSerializable()
class Participants {
  const Participants({required this.staffId, this.minutes});

  factory Participants.fromJson(Map<String, Object?> json) =>
      _$ParticipantsFromJson(json);

  @JsonKey(name: 'staff_id')
  final int staffId;
  final int? minutes;

  Map<String, Object?> toJson() => _$ParticipantsToJson(this);
}
