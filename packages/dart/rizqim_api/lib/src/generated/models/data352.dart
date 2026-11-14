// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries12.dart';
import 'summary13.dart';

part 'data352.g.dart';

@JsonSerializable()
class Data352 {
  const Data352({
    required this.branchId,
    required this.date,
    required this.entries,
    required this.summary,
  });

  factory Data352.fromJson(Map<String, Object?> json) =>
      _$Data352FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String date;
  final List<Entries12> entries;
  final Summary13 summary;

  Map<String, Object?> toJson() => _$Data352ToJson(this);
}
