// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'batches2.dart';
import 'days.dart';
import 'write_offs2.dart';

part 'data34.g.dart';

@JsonSerializable()
class Data34 {
  const Data34({
    required this.branchId,
    required this.from,
    required this.to,
    required this.batches,
    required this.days,
    required this.writeOffs,
    required this.inSets,
  });

  factory Data34.fromJson(Map<String, Object?> json) => _$Data34FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<Batches2> batches;
  final List<Days> days;
  @JsonKey(name: 'write_offs')
  final List<WriteOffs2> writeOffs;
  @JsonKey(name: 'in_sets')
  final List<dynamic> inSets;

  Map<String, Object?> toJson() => _$Data34ToJson(this);
}
