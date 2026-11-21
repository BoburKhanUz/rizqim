// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'summary13.dart';
import 'tips16.dart';

part 'data352.g.dart';

@JsonSerializable()
class Data352 {
  const Data352({
    required this.shiftUuid,
    required this.businessDate,
    required this.ownOnly,
    required this.summary,
    required this.tips,
  });

  factory Data352.fromJson(Map<String, Object?> json) =>
      _$Data352FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'own_only')
  final bool ownOnly;
  final Summary13 summary;
  final List<Tips16> tips;

  Map<String, Object?> toJson() => _$Data352ToJson(this);
}
