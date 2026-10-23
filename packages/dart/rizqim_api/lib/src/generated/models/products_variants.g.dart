// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products_variants.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ProductsVariants _$ProductsVariantsFromJson(Map<String, dynamic> json) =>
    ProductsVariants(
      id: (json['id'] as num).toInt(),
      uuid: json['uuid'] as String,
      productId: (json['product_id'] as num).toInt(),
      name: json['name'] as String?,
      sku: json['sku'] as String?,
      price: json['price'] as String,
      stock: json['stock'] as num,
      attributesJson: json['attributes_json'] as Map<String, dynamic>?,
      isActive: json['is_active'] as bool,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      deletedAt: json['deleted_at'] == null
          ? null
          : DateTime.parse(json['deleted_at'] as String),
      unit: MenuUnit.fromJson(json['unit'] as String),
      quantityStep: json['quantity_step'] as String?,
      minQuantity: json['min_quantity'] as String?,
      batchConsumption: json['batch_consumption'] as String,
      unitLabel: json['unit_label'] as String?,
      basePrice: json['base_price'] as String,
      isAvailable: json['is_available'] as bool,
      unavailableReason: json['unavailable_reason'] == null
          ? null
          : VariantsUnavailableReason.fromJson(
              json['unavailable_reason'] as String?,
            ),
      product: json['product'] == null
          ? null
          : Product.fromJson(json['product'] as Map<String, dynamic>),
      channelPrices: (json['channel_prices'] as List<dynamic>?)
          ?.map((e) => ChannelPrice.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$ProductsVariantsToJson(ProductsVariants instance) =>
    <String, dynamic>{
      'id': instance.id,
      'uuid': instance.uuid,
      'product_id': instance.productId,
      'name': instance.name,
      'sku': instance.sku,
      'price': instance.price,
      'stock': instance.stock,
      'attributes_json': instance.attributesJson,
      'is_active': instance.isActive,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'deleted_at': instance.deletedAt?.toIso8601String(),
      'unit': instance.unit,
      'quantity_step': instance.quantityStep,
      'min_quantity': instance.minQuantity,
      'batch_consumption': instance.batchConsumption,
      'unit_label': instance.unitLabel,
      'product': instance.product,
      'channel_prices': instance.channelPrices,
      'base_price': instance.basePrice,
      'is_available': instance.isAvailable,
      'unavailable_reason': instance.unavailableReason,
    };
