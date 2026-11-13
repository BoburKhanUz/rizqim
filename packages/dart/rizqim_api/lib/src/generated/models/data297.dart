// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'summary7.dart';
import 'tips15.dart';

part 'data297.g.dart';

@JsonSerializable()
class Data297 {
  const Data297({
    required this.shiftUuid,
    required this.businessDate,
    required this.ownOnly,
    required this.summary,
    required this.tips,
  });

  factory Data297.fromJson(Map<String, Object?> json) =>
      _$Data297FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'own_only')
  final bool ownOnly;
  final Summary7 summary;
  final List<Tips15> tips;

  Map<String, Object?> toJson() => _$Data297ToJson(this);
}
