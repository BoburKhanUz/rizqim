// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data173.g.dart';

@JsonSerializable()
class Data173 {
  const Data173({
    required this.uuid,
    required this.branchId,
    required this.userId,
    required this.userName,
    required this.businessDate,
    required this.clockInAt,
    required this.clockOutAt,
    required this.abandoned,
    required this.open,
    required this.minutes,
    required this.source,
    required this.note,
  });

  factory Data173.fromJson(Map<String, Object?> json) =>
      _$Data173FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'user_name')
  final String? userName;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'clock_in_at')
  final String clockInAt;
  @JsonKey(name: 'clock_out_at')
  final String? clockOutAt;
  final bool abandoned;
  final bool open;
  final int minutes;
  final String source;
  final String? note;

  Map<String, Object?> toJson() => _$Data173ToJson(this);
}
