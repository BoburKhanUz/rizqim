// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rooms.dart';
import 'unassigned.dart';

part 'data124.g.dart';

@JsonSerializable()
class Data124 {
  const Data124({
    required this.branchId,
    required this.from,
    required this.to,
    required this.timezone,
    required this.rooms,
    required this.unassigned,
  });

  factory Data124.fromJson(Map<String, Object?> json) =>
      _$Data124FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final String timezone;
  final List<Rooms> rooms;
  final List<Unassigned> unassigned;

  Map<String, Object?> toJson() => _$Data124ToJson(this);
}
