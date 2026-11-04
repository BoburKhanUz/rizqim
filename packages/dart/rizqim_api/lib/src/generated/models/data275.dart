// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'summary7.dart';
import 'tips14.dart';

part 'data275.g.dart';

@JsonSerializable()
class Data275 {
  const Data275({
    required this.shiftUuid,
    required this.businessDate,
    required this.ownOnly,
    required this.summary,
    required this.tips,
  });

  factory Data275.fromJson(Map<String, Object?> json) =>
      _$Data275FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'own_only')
  final bool ownOnly;
  final Summary7 summary;
  final List<Tips14> tips;

  Map<String, Object?> toJson() => _$Data275ToJson(this);
}
