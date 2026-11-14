// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold_out.dart';
import 'batches.dart';

part 'branches.g.dart';

@JsonSerializable()
class Branches {
  const Branches({
    required this.branchId,
    required this.stopped,
    required this.soldOut,
    required this.stopList,
    required this.stoppedIngredients,
    required this.limitedProducts,
    required this.nextBatches,
    required this.batches,
  });

  factory Branches.fromJson(Map<String, Object?> json) =>
      _$BranchesFromJson(json);

  @JsonKey(name: 'branch_id')
  final String branchId;
  final List<dynamic> stopped;
  @JsonKey(name: 'sold_out')
  final SoldOut soldOut;
  @JsonKey(name: 'stop_list')
  final List<dynamic> stopList;

  /// P6-55 / B9: the ingredients stopped on the branch ({id, ingredient_id, name, reason, until}) and the dishes a.
  /// non-critical link only marks ({product_id, ingredient_ids}: still sold, "tovuqsiz").
  @JsonKey(name: 'stopped_ingredients')
  final String stoppedIngredients;
  @JsonKey(name: 'limited_products')
  final List<dynamic> limitedProducts;
  @JsonKey(name: 'next_batches')
  final dynamic nextBatches;
  final List<Batches> batches;

  Map<String, Object?> toJson() => _$BranchesToJson(this);
}
