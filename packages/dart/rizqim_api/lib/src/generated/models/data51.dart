// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data51.g.dart';

@JsonSerializable()
class Data51 {
  const Data51({required this.branchId, required this.productIds});

  factory Data51.fromJson(Map<String, Object?> json) => _$Data51FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'product_ids')
  final List<dynamic> productIds;

  Map<String, Object?> toJson() => _$Data51ToJson(this);
}
